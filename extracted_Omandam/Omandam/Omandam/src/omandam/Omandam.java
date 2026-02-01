package omandam;

import java.util.Scanner;
import java.util.InputMismatchException;

public class Omandam {

    public static Scanner mj = new Scanner(System.in);
    static String nme;
    static int id;
    static int age;
    static String sex;
    static int year;
    static double salary;
    static String email;
    static String cpnum;
    public static Employee e = new Employee();
    public static Employee n = new Lawyer();
    public static Secretary s = new Secretary();
    public static Secretary c = new Legal_Secretary();
    public static Employee m = new Marketer();
    

    public static void main(String[] args) {
        while (true) {
            try {
                System.out.println();
                System.out.println("ROLE SELECTION: ");
                System.out.println("[1] Employee");
                System.out.println("[2] Lawyer");
                System.out.println("[3] Secretary");
                System.out.println("[4] Legal Secretary");
                System.out.println("[5] Marketer");
                System.out.println("[6] Exit");
                System.out.print("Choice: ");
                int choice = mj.nextInt();
                mj.nextLine();
                switch (choice) {
                    case 1:
                        employee();
                        edisplay();
                        break;
                    case 2:
                        lawyer();
                        ldisplay();
                        break;
                    case 3:
                       secretary();
                       sdisplay();
                        break;
                    case 4:
                       legal_secretary();
                       lsdisplay();
                        break;
                    case 5:
                       marketer();
                       mdisplay();
                        break;
                    case 6:
                        System.out.println("Thank you!");
                        return;
                }
            } catch (InputMismatchException e) {
                System.out.println("Invalid Value.");
                System.out.println();
                mj.nextLine();
            }
        }
    }

    public static void employee() {

        System.out.print("Enter Employee's Name: ");
        nme = mj.nextLine();
        e.setData(nme, id, age, sex, salary, year);
        System.out.println("Enter Years of Service: ");
        year = mj.nextInt();
        e.setData(nme, id, age, sex, salary, year);
        System.out.println("Enter ID number: ");
        id = mj.nextInt();
        e.setData(nme, id, age, sex, salary, year);
        System.out.println("Enter Age of Employee: ");
        age = mj.nextInt();
        e.setData(nme, id, age, sex, salary, year);
        System.out.println("Enter Sex of Employee: ");
        mj.nextLine();
        sex = mj.nextLine();
        e.setData(nme, id, age, sex, salary, year);
        System.out.println("Enter Salary of Employee: ");
        salary = mj.nextDouble();
        e.setData(nme, id, age, sex, salary, year);
    }

    public static void edisplay() {
        System.out.println("-----------------");
        System.out.println("Employee's DATA");
        System.out.println("-----------------");
        System.out.println("Name: " + e.getName());
        System.out.println("Years of service: " + e.getYear());
        System.out.println("ID number: " + e.getID());
        System.out.println("Age: " + e.getAge());
        System.out.println("Sex: " + e.getSex());
        System.out.println("Salary: " + e.getSalary());
        System.out.println("Bonus: " + e.bonus());
    }

    public static void lawyer() {

        System.out.print("Enter Lawyer's Name: ");
        nme = mj.nextLine();
        n.setData(nme, id, age, sex, salary, year);
        System.out.print("Enter Years of Service: ");
        year = mj.nextInt();
        n.setData(nme, id, age, sex, salary, year);
        System.out.print("Enter ID number: ");
        id = mj.nextInt();
        n.setData(nme, id, age, sex, salary, year);
        System.out.print("Enter Age of Lawyer: ");
        age = mj.nextInt();
        n.setData(nme, id, age, sex, salary, year);
        System.out.print("Enter Sex of Lawyer: ");
        mj.nextLine();
        sex = mj.nextLine();
        n.setData(nme, id, age, sex, salary, year);
        System.out.print("Enter Salary of Lawyer: ");
        salary = mj.nextDouble();
        n.setData(nme, id, age, sex, salary, year);
    }

    public static void ldisplay() {
        System.out.println("--------------");
        System.out.println("Lawyer's DATA");
        System.out.println("--------------");
        System.out.println("Name: " + n.getName());
        System.out.println("Years of service: " + n.getYear());
        System.out.println("ID number: " + n.getID());
        System.out.println("Age: " + n.getAge());
        System.out.println("Sex: " + n.getSex());
        System.out.println("Salary: " + n.getSalary());
        System.out.println("Bonus: " + n.bonus());
    }

    public static void secretary() {
        System.out.print("Enter Secretary's Name: ");
        nme = mj.nextLine();
        s.setData(nme, id, age, sex, salary, year, email, cpnum);
        System.out.print("Enter Years of Service: ");
        year = mj.nextInt();
        s.setData(nme, id, age, sex, salary, year, email, cpnum);
        System.out.print("Enter ID number: ");
        id = mj.nextInt();
        s.setData(nme, id, age, sex, salary, year, email, cpnum);
        System.out.print("Enter Age of Secretary: ");
        age = mj.nextInt();
        s.setData(nme, id, age, sex, salary, year, email, cpnum);
        System.out.print("Enter Sex of Secretary: ");
        mj.nextLine();
        sex = mj.nextLine();
        s.setData(nme, id, age, sex, salary, year, email, cpnum);
        System.out.print("Enter Salary of Secretary: ");
        salary = mj.nextDouble();
        s.setData(nme, id, age, sex, salary, year, email, cpnum);
        System.out.print("Enter Secretary's Email: ");
        mj.nextLine();
        email = mj.nextLine();
        s.setData(nme, id, age, sex, salary, year, email, cpnum);
        System.out.print("Enter Secretary's Cellphone Number: ");
        cpnum = mj.nextLine();
        s.setData(nme, id, age, sex, salary, year, email, cpnum);

    }

    public static void sdisplay() {
        System.out.println("-----------------");
        System.out.println("Secretary's DATA");
        System.out.println("-----------------");
        System.out.println("Name: " + s.getName());
        System.out.println("Years of service: " + s.getYear());
        System.out.println("ID number: " + s.getID());
        System.out.println("Age: " + s.getAge());
        System.out.println("Sex: " + s.getSex());
        System.out.println("Salary: " + s.getSalary());
        System.out.println("Email: " + s.getEmail());
        System.out.println("Cellphone Number: " + s.getCellnum());
        System.out.println("Bonus: " + s.bonus());
    }

    public static void legal_secretary() {
        System.out.print("Enter Legal Secretary's Name: ");
        nme = mj.nextLine();
        c.setData(nme, id, age, sex, salary, year, email, cpnum);
        System.out.print("Enter Years of Service: ");
        year = mj.nextInt();
        c.setData(nme, id, age, sex, salary, year, email, cpnum);
        System.out.print("Enter ID number: ");
        id = mj.nextInt();
        c.setData(nme, id, age, sex, salary, year, email, cpnum);
        System.out.print("Enter Age of Legal Secretary: ");
        age = mj.nextInt();
        c.setData(nme, id, age, sex, salary, year, email, cpnum);
        System.out.print("Enter Sex of Legal Secretary: ");
        mj.nextLine();
        sex = mj.nextLine();
        c.setData(nme, id, age, sex, salary, year, email, cpnum);
        System.out.print("Enter Salary of Legal Secretary: ");
        salary = mj.nextDouble();
        c.setData(nme, id, age, sex, salary, year, email, cpnum);
        System.out.print("Enter legal Secretary's Email: ");
        mj.nextLine();
        email = mj.nextLine();
        c.setData(nme, id, age, sex, salary, year, email, cpnum);
        System.out.print("Enter Legal Secretary's Cellphone Number: ");
        cpnum = mj.nextLine();
        c.setData(nme, id, age, sex, salary, year, email, cpnum);
    }
  public static void lsdisplay() {
        System.out.println("------------------------");
        System.out.println("Legal_Secretary's DATA");
        System.out.println("------------------------");
        System.out.println("Name: " + c.getName());
        System.out.println("Years of service: " + c.getYear());
        System.out.println("ID number: " + c.getID());
        System.out.println("Age: " + c.getAge());
        System.out.println("Sex: " + c.getSex());
        System.out.println("Salary: " + c.getSalary());
        System.out.println("Email: " + c.getEmail());
        System.out.println("Cellphone Number: " + c.getCellnum());
        System.out.println("Bonus: " + c.bonus());
  }
  public static void marketer(){
      System.out.print("Enter Marketer's Name: ");
        nme = mj.nextLine();
        m.setData(nme, id, age, sex, salary, year);
        System.out.print("Enter Years of Service: ");
        year = mj.nextInt();
        m.setData(nme, id, age, sex, salary, year);
        System.out.print("Enter ID number: ");
        id = mj.nextInt();
        m.setData(nme, id, age, sex, salary, year);
        System.out.print("Enter Age of Marketer: ");
        age = mj.nextInt();
        m.setData(nme, id, age, sex, salary, year);
        System.out.print("Enter Sex of Marketer: ");
        mj.nextLine();
        sex = mj.nextLine();
        m.setData(nme, id, age, sex, salary, year);
        System.out.print("Enter Salary of Marketer: ");
        salary = mj.nextDouble();
        m.setData(nme, id, age, sex, salary, year);
  }
 public static void mdisplay() {
        System.out.println("---------------");
        System.out.println("Marketer's DATA");
        System.out.println("---------------");
        System.out.println("Name: " + m.getName());
        System.out.println("Years of service: " + m.getYear());
        System.out.println("ID number: " + m.getID());
        System.out.println("Age: " + m.getAge());
        System.out.println("Sex: " + m.getSex());
        System.out.println("Salary: " + m.getSalary());
        System.out.println("Bonus: " + m.bonus());
}
}
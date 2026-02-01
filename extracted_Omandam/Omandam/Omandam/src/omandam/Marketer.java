package omandam;
public class Marketer extends Employee {
    public Marketer(){
        this(null,0,0,null,0,0);
    }
    public Marketer(String name,int id,int age,String sex,double salary,int year) {
     super(name, id, age, sex,salary,year);
      
        
    }
    @Override
    public String getName(){
        return super.getName();
    }
    @Override
    public int getID(){
        return super.getID();
    }
    @Override
    public int getAge(){
        return super.getAge();
    }
    @Override
    public String getSex(){
        return super.getSex();
    }
    @Override
    public double getSalary(){
      double marketer = super.getSalary();
      return marketer + 5000;
    }
    @Override
    public int getYear(){
    return super.getYear();
    }
    @Override
    public void setData(String name,int id,int age,String sex,double salary,int year){
       super.setData(name, id, age, sex, salary, year);
    }
    public double bunos(){
        return super.bonus();
    }
}

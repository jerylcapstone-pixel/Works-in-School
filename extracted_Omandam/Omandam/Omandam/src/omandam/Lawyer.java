package omandam;
public class Lawyer extends Employee {
    public Lawyer(){
        this(null,0,0,null,0,0);
    }
   public Lawyer(String name,int id,int age,String sex,double salary,int year){
   super(name,id,age,sex,salary,year);
        
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
      double lawyer = super.getSalary();
      return lawyer + 10000;
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

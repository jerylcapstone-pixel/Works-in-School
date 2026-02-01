package omandam;
public class Legal_Secretary extends Secretary{
    public Legal_Secretary(){
         this(null,0,0,null,0,0,null,null);
    }
    public Legal_Secretary(String name,int id,int age,String sex,double salary,int year,String email,String cpnum) {
        super(name,id,age,sex,salary,year,email,cpnum);
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
      double Secretary = super.getSalary();
      return Secretary + 20000;
    }
    @Override
    public int getYear(){
    return super.getYear();
    }
    @Override
    public String getEmail(){
        return super.getEmail();
    }
    @Override
    public String getCellnum(){
        return super.getCellnum();
    }
   
    @Override
    public void setData(String name,int id,int age,String sex,double salary,int year,String email, String cpnum){
       super.setData(name, id, age, sex, salary, year, email, cpnum);
    }
    public double bunos(){
        return super.bonus();
    }
}
